# CEMDB Integration Example

This document provides a practical example of how the CEMDB integration works.

## Scenario

You have a Cisco router monitored by Cacti with the following configuration:
- **Device Name**: core-router-01
- **IP Address**: 192.168.1.1
- **Interface**: GigabitEthernet0/1

## Without CEMDB Integration

When an interface goes down, the standard threshold alert looks like this:

```
Subject: ALERT: core-router-01 - Interface Status

An alert has been issued that requires your attention.

Device: core-router-01 (192.168.1.1)
Threshold: Interface GigabitEthernet0/1 Status
Current Value: DOWN
Threshold: Expected UP

Time: 2025-01-12 10:30:45
```

**Problem**: The alert tells you something is wrong, but doesn't provide:
- What the error code means
- Why it might have occurred
- What steps to take to fix it

## With CEMDB Integration Enabled

The same threshold alert is now enriched with CEMDB information:

```
Subject: ALERT: core-router-01 - Interface Status

An alert has been issued that requires your attention.

Device: core-router-01 (192.168.1.1)
Threshold: Interface GigabitEthernet0/1 Status
Current Value: DOWN
Threshold: Expected UP

Time: 2025-01-12 10:30:45

-------------------------------------------------------------------
CISCO ERROR DETAILS: %LINK-3-UPDOWN
-------------------------------------------------------------------

Description:
Interface status changed

Explanation:
The interface changed state to administratively down or physically 
down. This message appears when an interface transitions between 
up and down states.

Possible Causes:
- Physical layer problem (cable disconnected, bad port)
- Administrative shutdown
- Speed/duplex mismatch
- SFP module failure

Recommended Action:
1. Check physical connectivity (cables, fiber, SFP modules)
2. Verify interface is not administratively shutdown:
   show interface GigabitEthernet0/1
3. Check for error counters:
   show interface GigabitEthernet0/1 | include error
4. Verify speed/duplex settings match both ends
5. Check logs for additional related messages:
   show logging | include GigabitEthernet0/1

Related Error Codes:
- %LINEPROTO-5-UPDOWN (Layer 2 protocol state)
- %LINK-5-CHANGED (Interface status change)

Documentation:
https://www.cisco.com/c/en/us/td/docs/routers/...
-------------------------------------------------------------------
```

**Benefits**:
- ✅ Immediate context about what the error means
- ✅ Possible root causes identified
- ✅ Step-by-step troubleshooting guide
- ✅ Related error codes for correlation
- ✅ Direct links to Cisco documentation
- ✅ Faster mean time to resolution (MTTR)

## Real-World Impact

### Before CEMDB
1. Alert received → 5 minutes
2. Research error code → 10 minutes
3. Identify possible causes → 15 minutes
4. Find troubleshooting steps → 10 minutes
5. Begin resolution → 40 minutes total

**Total Time to Start Resolution: ~40 minutes**

### After CEMDB
1. Alert received with all context → 5 minutes
2. Begin resolution immediately → 5 minutes total

**Total Time to Start Resolution: ~5 minutes**
**Time Saved: 35 minutes per incident**

## Configuration Example

Here's how to configure CEMDB for this scenario:

### Step 1: Enable in Cacti

```
Configuration → Settings → Thresholds → CEMDB Integration
☑ Enable CEMDB Integration
API Key: [your-cisco-api-key]
API Timeout: 5 seconds
Cache TTL: 86400 seconds (24 hours)
```

### Step 2: Create Threshold

```
Graph: Interface - Traffic (bits/sec)
Data Source: traffic_in
Type: High/Low
High Threshold: 900000000 (900 Mbps)
Trigger: 2 polls
☑ Send Email Alert
☑ Enable Syslog
☑ Enable CEMDB Enrichment  ← New option
```

### Step 3: Test

```bash
# Simulate interface down
ssh admin@192.168.1.1
configure terminal
interface GigabitEthernet0/1
shutdown
```

Wait for next poller cycle, then check your email for the enriched alert!

## API Cost Analysis

### Without Caching (Bad Practice)
- Incidents per day: 50
- API calls per incident: 1
- Total API calls: 50/day = 1,500/month

### With CEMDB Caching (Best Practice)
- Incidents per day: 50
- Unique error codes: ~10
- API calls (first occurrence only): 10
- Subsequent incidents: Use cache
- Total API calls: 10/day initially, then ~0.1/day
- **Cost Reduction: ~99%**

## Error Code Coverage

CEMDB integration works with all standard Cisco error message formats:

```
%FACILITY-SEVERITY-MNEMONIC: Description

Examples:
✅ %SYS-5-CONFIG_I: Configured from console
✅ %LINK-3-UPDOWN: Interface changed state
✅ %LINEPROTO-5-UPDOWN: Line protocol changed
✅ %BGP-5-ADJCHANGE: Neighbor state changed
✅ %OSPF-5-ADJCHG: Process, Nbr state changed
✅ %HSRP-6-STATECHANGE: Group state changed
```

## Multi-Device Support

CEMDB enrichment works across your entire Cisco infrastructure:

```
Routers:
- Core routers (Cisco ASR, ISR, etc.)
- Branch routers
- WAN edge devices

Switches:
- Core switches (Cisco Catalyst, Nexus)
- Distribution switches
- Access switches

Wireless:
- Wireless controllers
- Access points (via controller)

Security:
- Firewalls (ASA, Firepower)
- IPS/IDS devices
```

## Conclusion

CEMDB integration transforms basic threshold alerts into actionable intelligence, 
dramatically reducing incident response time and improving network reliability.

---
Copyright (C) 2004-2025 The Cacti Group
